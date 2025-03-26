import React, { useState, useCallback, useMemo } from "react";
import axios from "axios";

function App() {
	const [resumes, setResumes] = useState([]);
	const [jobDesc, setJobDesc] = useState("");
	const [results, setResults] = useState([]);
	const [loading, setLoading] = useState(false);

	const handleFileChange = useCallback((e) => {
		setResumes(Array.from(e.target.files));
	}, []);

	const handleSubmit = useCallback(async () => {
		if (!jobDesc.trim() || resumes.length === 0) {
			alert("Please add a job description and at least one resume.");
			return;
		}

		const formData = new FormData();
		resumes.forEach((file) => formData.append("resumes[]", file));
		formData.append("job_description", jobDesc);

		setLoading(true);
		try {
			const res = await axios.post(
				"http://localhost:8000/backend.php",
				formData
			);

			if (res.status === 200) {
				setResults(res.data);
			} else {
				alert("Unexpected server response.");
			}
		} catch (err) {
			console.error(err);
			alert("Error processing resumes.");
		} finally {
			setLoading(false);
		}
	}, [resumes, jobDesc]);

	return (
		<div className='min-h-screen bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center px-4 py-8'>
			<div className='w-full max-w-xl bg-white rounded-2xl shadow-2xl overflow-hidden'>
				<div className='bg-blue-600 text-white p-6'>
					<h2 className='text-3xl font-bold text-center flex items-center justify-center gap-3'>
						<svg
							xmlns='http://www.w3.org/2000/svg'
							className='h-8 w-8'
							viewBox='0 0 24 24'
							fill='none'
							stroke='currentColor'
							strokeWidth='2'
							strokeLinecap='round'
							strokeLinejoin='round'
						>
							<path d='M16 3h5v5' />
							<path d='M4 22h5v-5' />
							<path d='M15 3h4a2 2 0 0 1 2 2v3' />
							<path d='M4 19v-3a2 2 0 0 1 2-2h3' />
							<path d='m21 11-5-5' />
							<path d='m3 21 5-5' />
						</svg>
						AI Resume Matcher
					</h2>
				</div>

				<div className='p-6 space-y-6'>
					<div>
						<label className='block text-sm font-medium text-gray-700 mb-2'>
							Job Description
						</label>
						<textarea
							className='w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200 resize-none'
							placeholder='Paste detailed job description here...'
							value={jobDesc}
							onChange={(e) => setJobDesc(e.target.value)}
							rows={6}
						/>
					</div>

					<div>
						<label className='block text-sm font-medium text-gray-700 mb-2'>
							Upload Resumes (PDF only)
						</label>
						<div className='relative border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-500 transition'>
							<input
								type='file'
								multiple
								accept='.pdf'
								onChange={handleFileChange}
								className='absolute inset-0 w-full h-full opacity-0 cursor-pointer'
							/>
							<div className='text-gray-600'>
								{resumes.length > 0
									? `${resumes.length} file(s) selected`
									: "Drag and drop PDF files or click to upload"}
							</div>
						</div>
					</div>

					<button
						onClick={handleSubmit}
						disabled={loading}
						className='w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition duration-300 flex items-center justify-center disabled:opacity-50'
					>
						{loading ? (
							<svg className='animate-spin h-5 w-5 mr-3' viewBox='0 0 24 24'>
								<circle
									className='opacity-25'
									cx='12'
									cy='12'
									r='10'
									stroke='currentColor'
									strokeWidth='4'
								></circle>
								<path
									className='opacity-75'
									fill='currentColor'
									d='M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z'
								></path>
							</svg>
						) : (
							"Process Resumes"
						)}
					</button>

					{results.length > 0 && (
						<div className='mt-6'>
							<h3 className='text-xl font-semibold mb-4 text-gray-800'>
								Ranked Resumes
							</h3>
							<div className='space-y-3'>
								{results.map((res, index) => (
									<div
										key={res.filename}
										className={`
                      p-4 rounded-lg shadow-md transition-all duration-300
                      ${
												index < 5
													? "bg-green-50 border-l-4 border-green-500 hover:bg-green-100"
													: "bg-white border-l-4 border-gray-200 hover:bg-gray-50"
											}
                    `}
									>
										<div className='flex justify-between items-center'>
											<span className='font-medium text-gray-800'>
												{res.filename}
											</span>
											<span
												className={`
                          px-3 py-1 rounded-full text-sm font-semibold
                          ${
														index < 5
															? "bg-green-200 text-green-800"
															: "bg-gray-200 text-gray-800"
													}
                        `}
											>
												Score: {res.score}
											</span>
										</div>
									</div>
								))}
							</div>
						</div>
					)}
				</div>
			</div>
		</div>
	);
}

export default App;
